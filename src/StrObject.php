<?php

declare(strict_types=1);

namespace net\lapaphp\dotenv;

use Countable;
use InvalidArgumentException;

use function str_replace;
use function ltrim;
use function rtrim;
use function trim;
use function get_class;
use function array_filter;
use function array_map;
use function explode;
use function mb_strlen;
use function mb_strpos;
use function mb_substr;
use function mb_strwidth;

/**
 * Smart utility for operations with multibyte strings
 *
 * @required ext-mbstring
 * @property-read int $length Length of string
 * @property-read int $width Count of symbols
 */
final class StrObject implements Countable
{
    /**
     * @var string
     */
    private $str;

    /**
     * Constructor
     *
     * @param mixed $str
     */
    public function __construct($str = '')
    {
        $str = (string)$str;
        $this->set($str);
    }

    public function __get($name)
    {
        switch ($name) {
            case 'length':
                return mb_strlen($this->str);
            case 'width':
                return mb_strwidth($this->str);
            default:
                throw new InvalidArgumentException("Unknown getter '$name'");
        }
    }

    public function __toString()
    {
        return $this->str;
    }

    /**
     * Clear casting an object to a string type
     *
     * @return string
     */
    public function toString(): string
    {
        return $this->str;
    }

    /**
     * Get count all chars. Result same as {@see $length}
     *
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        return $this->length;
    }

    /**
     * Getter
     *
     * @return string
     */
    protected function get(): string
    {
        return $this->str;
    }

    /**
     * Setter
     *
     * @param string $str
     */
    protected function set(string $str): void
    {
        $this->str = $str;
    }

    /**
     * Check object by empty string
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->str === '';
    }

    /**
     * Check equivalence with object <var>$obj</var>
     *
     * @param self $obj
     * @return bool
     */
    public function eq(self $obj): bool
    {
        return __CLASS__ === get_class($obj) && $this->str === $obj->str;
    }

    /**
     * Find position of first occurrence of string in a string
     *
     * @param string $str
     * @param int $from
     * @return int|false
     */
    public function pos(string $str, int $from = 0)
    {
        return mb_strpos($this->str, $str, $from);
    }

    /**
     * Replace to part of string
     *
     * @param int $start
     * @param ?int $length
     * @return self|static
     */
    public function substr(int $start, int $length = null): self
    {
        if (null === $length) {
            $s = mb_substr($this->str, $start);
        } else {
            $s = mb_substr($this->str, $start, $length);
        }
        $this->set($s);
        return $this;
    }

    /**
     * Get character by index. First character's position is 0. Second character
     * position is 1, and so on. Returns empty string if
     * abs(<var>$index</var>) > {@see Strk::$length}
     *
     * @param int $index
     * @return string
     */
    public function charAt(int $index): string
    {
        return mb_substr($this->str, $index, 1);
    }

    /**
     * Add string in the end of self
     *
     * @param string $str
     * @param int $repeats
     * @return self|static
     */
    public function append(string $str, int $repeats = 1): self
    {
        for ($i = 0; $i < $repeats; $i++) {
            $this->set($this->str . $str);
        }
        return $this;
    }

    /**
     * Add string in the beginning of self
     *
     * @param string $str
     * @param int $repeats
     * @return self|static
     */
    public function prepend(string $str, int $repeats = 1): self
    {
        for ($i = 0; $i < $repeats; $i++) {
            $this->set($str . $this->str);
        }
        return $this;
    }

    /**
     * Strip whitespace (or other characters)
     * from the beginning and end of self
     *
     * @param string $charList
     * @return self|static
     */
    public function trim(string $charList = ""): self
    {
        $this->set($charList ? trim($this->str, $charList) : trim($this->str));
        return $this;
    }

    /**
     * Strip whitespace (or other characters) from the beginning of self
     *
     * @param string $charList
     * @return self|static
     */
    public function trimLeft(string $charList = ""): self
    {
        $this->set($charList ? ltrim($this->str, $charList) : ltrim($this->str));
        return $this;
    }

    /**
     * Strip whitespace (or other characters) from the beginning of self
     *
     * @param string $charList
     * @return self|static
     */
    public function trimRight(string $charList = ""): self
    {
        $this->set($charList ? rtrim($this->str, $charList) : rtrim($this->str));
        return $this;
    }

    /**
     * Returns an object containing string that was be found after <var>$str</var>
     * but after <var>$offset</var>
     *
     * <code>
     *    $o = new self('a+b+c=12');
     *    $o->after('=')->out(); // "12"
     * </code>
     *
     * @param string $str
     * @param int $offset
     * @return self|static|null
     */
    public function after(string $str, int $offset = 0): ?self
    {
        $pos = mb_strpos($this->str, $str, $offset);
        if (false === $pos) {
            return null;
        }
        return $this->substr($pos + mb_strlen($str));
    }

    /**
     * Returns an object containing string that was be found before <var>$str</var>
     * but after <var>$offset</var>
     *
     * <code>
     *    $o = new self('2x*y=z');
     *    $o->before('*')->out(); // equal "2x"
     * </code>
     *
     * @param string $str
     * @param int $offset
     * @return self|static|null
     */
    public function before(string $str, int $offset = 0): ?self
    {
        $pos = mb_strpos($this->str, $str, $offset);
        if (false === $pos) {
            return null;
        }
        return $this->substr(0, $pos);
    }

    /**
     * Returns <code>true</code> if string data starts with a given <var>$str</var>.
     *
     * @param string $str
     * @return bool
     */
    public function begins(string $str): bool
    {
        if (PHP_VERSION_ID >= 80000) {
            return str_starts_with($this->str, $str);
        }
        if ($str) {
            return 0 === strncmp($this->str, $str, strlen($str));
        }
        return true;
    }

    /**
     * Returns <code>true</code> if string data contains a given <var>$str</var>
     *
     * @param string $str
     * @return bool
     */
    public function contains(string $str): bool
    {
        if (PHP_VERSION_ID >= 80000) {
            return str_contains($this->str, $str);
        }
        if ($str) {
            return $this->pos($str) !== false;
        }
        return true;
    }

    /**
     * Returns <code>true</code> if string data ends with a given <var>$str</var>
     *
     * @param string $str
     * @return bool
     */
    public function ends(string $str): bool
    {
        if (PHP_VERSION_ID >= 80000) {
            return str_ends_with($this->str, $str);
        }
        if ($str) {
            $length = strlen($str);
            return 0 === $length || (substr($this->str, -$length) === $str);
        }
        return true;
    }

    /**
     * Replaces all whitespaces to one space
     *
     * @return self|static
     */
    public function toLine(): self
    {
        $this->set(trim(preg_replace('/\s+/', ' ', $this->get())));
        return $this;
    }

    /**
     * Replaces end of line as for UNIX
     *
     * @return self|static
     */
    public function toUnix(): self
    {
        $this->set(str_replace(["\r\n", "\r"], "\n", $this->get()));
        return $this;
    }

    public function isWhitespaces(): bool
    {
        return '' === ltrim($this->get());
    }

    /**
     * Split string data by a string
     *
     * @param string $separator
     * @param int $limit
     * @return self[]
     */
    public function split(string $separator, int $limit = -1): array
    {
        if ($limit > 0) {
            $a = explode($separator, $this->get(), $limit);
        } else {
            $a = explode($separator, $this->get());
        }
        return self::mapToSelf($a);
    }

    /**
     * Divide text into lines by UNIX EOL
     *
     * @param bool $onlyLines
     * @param bool $fixToUnixEol
     * @return self[]
     */
    public function lines(bool $onlyLines = false, bool $fixToUnixEol = false): array
    {
        $content = $fixToUnixEol ? $this->toUnix()->get() : $this->get();
        $a = self::explodeWith($sep = "\n", $content);

        if ($onlyLines) {
            $a = array_filter($a, static function ($item) use ($sep) {
                return $item && $item !== $sep;
            });
        }
        return self::mapToSelf($a);
    }

    protected static function explodeWith(string $sep, string $content): array
    {
        $exploded = explode($sep, $content);
        $a = [];
        foreach ($exploded as $i => $line) {
            $a[] = $line;
            if (isset($exploded[$i + 1])) {
                $a[] = $sep;
            }
        }
        return $a;
    }

    /**
     * Returns clone of instance
     *
     * @return self|static
     */
    public function clon(): self
    {
        return clone $this;
    }

    /**
     * Static constructor
     *
     * @param mixed $str
     * @return self|static
     */
    public static function create($str = ''): self
    {
        return new static($str);
    }

    /**
     * Wrap string to objects
     *
     * @param string[] $a
     * @return self[]
     */
    protected static function mapToSelf(array $a): array
    {
        return array_map(static function ($s) {
            return new static($s);
        }, $a);
    }
}
