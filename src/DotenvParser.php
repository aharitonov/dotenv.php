<?php

declare(strict_types=1);

namespace net\lapaphp\dotenv;

use Generator;
use Throwable;
use RuntimeException;
use net\lapaphp\dotenv\StrObject as Strk;

use function array_slice;
use function count;
use function explode;
use function ltrim;
use function trim;
use function strpos;
use function substr;
use function preg_replace;
use function preg_replace_callback;

/**
 * Parser for dot-env-files
 *
 * Example:
 * <code>
 *    $array = DotenvParser::fromFile('path/to/.env', $_ENV);
 *    foreach ($array as $name => $value) {
 *        putenv("$name=$value");
 *    }
 *
 *    foreach ($array as $name => $value) {
 *        print $name . ' => ' . getenv($name) . PHP_EOL;
 *    }
 * </code>
 */
class DotenvParser
{
    private const STATE_NORM = 0;
    private const STATE_SQ = 1;
    private const STATE_DQ = 2;
    private const INLINE_WHITESPACES = " \t";

    private $escFlag = false;
    private $state = self::STATE_NORM;
    private $curLineNum;
    private $env;

    public function __construct(array $predefinedEnv = [])
    {
        $this->env = $predefinedEnv;
    }

    /**
     * @param string $envContent
     * @return Generator|Strk[]
     */
    private function toLines(string $envContent): Generator
    {
        $lines = Strk::create($envContent)->lines(false, true);
        $lines = array_filter($lines, static function (Strk $s) {
            return $s->toString() !== "\n";
        });
        foreach ($lines as $line) {
            yield $line;
        }
    }

    /**
     * @param string $envContent
     * @return array
     * @throws DotenvException
     */
    public function parse(string $envContent): array
    {
        if (false === strpos($envContent, '=')) { // optimization
            return $this->env;
        }

        $dataEnv = [];
        $lines = $this->toLines($envContent);
        $line = $lines->current();
        $this->curLineNum = 1;

        while ($lines->valid()) {
            if ($line->isEmpty()) {
                $lines->next();
                $line = $lines->current();
                $this->curLineNum++;
                if (!$line || $line->isEmpty()) {
                    continue;
                }
            }

            if ($this->state === self::STATE_NORM) {
                $line->trimLeft(self::INLINE_WHITESPACES);
                if ($line->isEmpty() || $line->begins('#')) {
                    $line = Strk::create(); // set empty
                    continue;
                }

                $parts = $line->split('=', 2);
                if (!isset($parts[0]) || $parts[0]->isEmpty()) {
                    throw $this->newSyntaxException('No left part');
                }

                $left = $parts[0];
                if ($left->begins('export')) {
                    /** @noinspection NullPointerExceptionInspection */
                    $left->after('export')->trimLeft(self::INLINE_WHITESPACES);
                }

                if (isset($parts[1])) {
                    $dataEnv[$lastKey = $left->toString()] = [];
                    $rawRight = $parts[1];
                } else {
                    $rawRight = $parts[0];
                }

                if (!$rawRight->clon()->trimLeft(self::INLINE_WHITESPACES)->eq($rawRight)) {
                    throw $this->newSyntaxException(
                        'Whitespaces can not be used as delimiters'
                    );
                }
                [$right, $i, $mustBeResolved] = $this->parseRight($rawRight);
                $line = $rawRight->substr($i);
                if ($right) {
                    $dataEnv[$lastKey][] = [$right, $mustBeResolved];
                    if ($this->escFlag) {
                        $dataEnv[$lastKey][] = [' ', false];
                    }
                }
            } else {
                do {
                    [$right, $i, $mustBeResolved] = $this->parseRight($line);
                    $line->substr($i);
                    if ($right) {
                        $dataEnv[$lastKey][] = [$right, $mustBeResolved];
                    }
                } while (self::needToContinueParseRightPart($line));

                if ($this->state !== self::STATE_NORM) {
                    $dataEnv[$lastKey][] = [' ', false];
                }
            }
        }

        if ($this->state !== self::STATE_NORM) {
            throw $this->newSyntaxException('Broken quotes');
        }

        $dataEnv = $this->reduceWhitespaces($dataEnv);

        foreach ($dataEnv as $k => $items) {
            $s = '';
            foreach ($items as [$v, $mbr]) {
                $s .= ($mbr ? $this->resolve($v) : $v);
            }
            $this->env[$k] = $s;
        }

        return $this->env;
    }

    private function reduceWhitespaces(array $dataEnv): array
    {
        foreach ($dataEnv as &$items) {
            if (empty($items)) {
                $items = ['', false];
                continue;
            }

            foreach ($items as &$item) {
                $item[2] = Strk::create($item[0])->isWhitespaces();
            }
            unset($item);

            $indexMin = 0;
            foreach ($items as $i => $item) {
                if (false === $item[2]) {
                    $indexMin = $i;
                    break;
                }
            }

            $indexMax = 0;
            for ($i = count($items) - 1; $i >= 0; $i--) {
                if (false === $items[$i][2]) {
                    $indexMax = $i;
                    break;
                }
            }

            if ($indexMin === $indexMax) {
                $one = $items[$indexMin];
                $items = [
                    [trim($one[0]), $one[1]]
                ];
            } else {
                $items = array_slice($items, $indexMin, $indexMax + 1);
                $newItems = [];
                foreach ($items as $i => $item) {
                    if (!$item[2]) {
                        $newItems[] = [trim($item[0]), $item[1]];
                        continue;
                    }
                    if (isset($items[$i - 1]) && $items[$i - 1][2]) {
                        continue; // detected repeating whitespace chunks
                    }
                    $newItems[] = [' ', $items[$i][1]];
                }
                $items = $newItems;
            }
        }

        return $dataEnv;
    }

    /**
     * <code>
     *    [$outString, $lastPosition, $mustBeResolved] = $this->parseRight($rawRight);
     * </code>
     *
     * @param Strk $rawString
     * @return array
     */
    private function parseRight(Strk $rawString): array
    {
        try {
            return $this->parseLine($rawString);
        } catch (Throwable $e) {
            throw $this->newSyntaxException(
                sprintf('Can not parse string [%s]', $rawString),
                1,
                $e
            );
        }
    }

    private function parseLine(Strk $rawString): array
    {
        $mustBeResolvedFlag = true;
        $escFlag = &$this->escFlag;
        $stack = '';

        for ($i = 0, $iMax = $rawString->length; $iMax > $i; $i++) {
            $char = $rawString->charAt($i);

            if ($escFlag) {
                $stack .= $char;
                $escFlag = false;
                continue;
            }

            // $escFlag = FALSE
            switch ($char) {
                case '"':
                    if ($this->state === self::STATE_NORM) {
                        $this->state = self::STATE_DQ;
                        continue 2;
                    }
                    if ($this->state === self::STATE_DQ) {
                        $this->state = self::STATE_NORM;
                        continue 2;
                    }
                    break;
                case "'":
                    if ($this->state === self::STATE_NORM) {
                        $this->state = self::STATE_SQ;
                        $mustBeResolvedFlag = true;
                        $i++;
                        break 2;
                    }
                    if ($this->state === self::STATE_SQ) {
                        $this->state = self::STATE_NORM;
                        $mustBeResolvedFlag = false;
                        $i++;
                        break 2;
                    }
                    break;
                case '\\':
                    if ($this->state !== self::STATE_SQ) {
                        $escFlag = true;
                        $t = $rawString->clon()->substr($i + 1);
                        $onlyWs = $t->length && $t->trimLeft(self::INLINE_WHITESPACES)->isEmpty();
                        if ($onlyWs) {
                            break 2;
                        }
                        continue 2;
                    }
                    break;
                case '#':
                    $prevChar = $rawString->charAt($i - 1);
                    if (ltrim($prevChar, self::INLINE_WHITESPACES) !== $prevChar) {
                        // preceding character is whitespace
                        break 2;
                    }
                    break;
                case ';':
                    if ($this->state === self::STATE_NORM) {
                        $i++;
                        break 2;
                    }
                    break;
                default:
                    if ($this->state === self::STATE_NORM) {
                        if (Strk::create($char)->isWhitespaces()) {
                            break 2;
                        }
                    }
            }

            $stack .= $char;
            $escFlag = false;
        }

        return [$stack, $i, $mustBeResolvedFlag];
    }

    private function resolve(string $str): string
    {
        $str = preg_replace('~\$([\w_]+)~', '${$1}', $str);
        return preg_replace_callback(
            '~(\\${.+})~',
            function (array $out) {
                return $this->resolveRecursive($out[1]);
            },
            $str
        );
    }

    private function resolveRecursive(string $str)
    {
        $str = self::unwrap($str);
        $pair = explode(':-', $str, 2);
        if (0 === strpos($pair[0], '$')) {
            //return $this->resolveRecursive($pair[0]); // bash restriction
            throw new DotenvException(
                sprintf('Invalid substitution in expression [%s]', $str)
            );
        }
        $x = $this->env[$pair[0]] ?? '';
        if (!$x) {
            if (isset($pair[1])) {
                if (0 === strpos($pair[1], '$')) {
                    $x = $this->resolveRecursive($pair[1]);
                    if ($x) {
                        return $x;
                    }
                }
                return $pair[1];
            }
            return '';
        }
        return $x;
    }

    private function newSyntaxException(
        string    $message,
        int       $code = 0,
        Throwable $prev = null
    ): DotenvException {
        $s = 'Syntax error in line ' . $this->curLineNum . '. ' . $message;
        return new DotenvException($s, $code, $prev);
    }

    private static function unwrap(string $variableNotation): string
    {
        $str = trim($variableNotation, self::INLINE_WHITESPACES);
        $str = self::unwrapLeft($str, '$');
        $str = self::unwrapLeft($str, '{');
        $str = self::unwrapRight($str, '}');
        return $str;
    }

    private static function unwrapLeft(string $str, string $letter): string
    {
        $char = $str[0];
        if ($char === $letter) {
            return substr($str, 1);
        }
        return $str;
    }

    private static function unwrapRight(string $str, string $letter): string
    {
        $lastChar = substr($str, -1);
        if ($lastChar === $letter) {
            return substr($str, 0, -1);
        }
        return $str;
    }

    private static function needToContinueParseRightPart(Strk $line): bool
    {
        return $line->length && $line
                ->clon()
                ->substr(0, 1)
                ->trimLeft(self::INLINE_WHITESPACES)
                ->length;
    }

    /**
     * Load variables from file
     *
     * <code>
     *    $vars = fromFile('path/to/.env', $_ENV);
     * </code>
     *
     * @param string $filePath
     * @param array $predefinedEnv
     * @return array
     * @throws DotenvException
     */
    public static function fromFile(string $filePath, array $predefinedEnv = []): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException(
                sprintf('Bad path or file not found [%s]', $filePath)
            );
        }
        $envContent = file_get_contents($filePath);
        return (new static($predefinedEnv))->parse($envContent);
    }
}
