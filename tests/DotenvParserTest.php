<?php

use net\lapaphp\dotenv\DotenvParser;
use PHPUnit\Framework\TestCase;

class DotenvParserTest extends TestCase
{
    public function testEmpty()
    {
        $env = ['A' => 'B'];

        $result = (new DotenvParser($env))->parse('');
        self::assertSame($result, $env);

        $result = (new DotenvParser($env))->parse(" \t\r\n \n");
        self::assertSame($result, $env);
    }

    public function testNames()
    {
        $spaces = "\t    \t";
        $result = (new DotenvParser())->parse(<<<ENV
        МАША=val1
        ВАСЯ==val2$spaces

        export ПЕТЯ=val3 КАТЯ=val4
        export МИТЯ=val5
        ENV);

        self::assertSame($result, [
            'МАША' => 'val1',
            'ВАСЯ' => '=val2',
            'ПЕТЯ' => 'val3',
            'КАТЯ' => 'val4',
            'МИТЯ' => 'val5',
        ]);
    }

    public function testSemicolon()
    {
        $result = (new DotenvParser())->parse(<<<'ENV'
        A=BCD;
        export K1=value_1 K2=value_2;
            K3=VAL3\;; K4=VAL4
        export K1=value_1+; K1=value_1++
        ENV);

        self::assertSame($result, [
            'A'  => 'BCD',
            'K1' => 'value_1++',
            'K2' => 'value_2',
            'K3' => 'VAL3;',
            'K4' => 'VAL4',
        ]);
    }

    public function testQuotes1()
    {
        $result = (new DotenvParser())->parse(<<<'ENV'
            DED="""",
            BABA=''''
            DED1="BABA1" DED2="BABA 2" DED3="BABA 	3"
            
                k_1="v1" k_2=v2
            
            DED4='BABA4' DED5='BABA 5' DED6='BABA 	6'
            
                k_1="  'v1 v2'"\ " "
            k_2=" '''v1\"\" '\"' ''v2\"\" "
        ENV);

        self::assertSame($result, [
            'DED'  => ',',
            'BABA' => '',
            'DED1' => 'BABA1',
            'DED2' => 'BABA 2',
            'DED3' => 'BABA 	3',
            'k_1'  => "'v1 v2'",
            'k_2'  => "'''v1\"\" '\"' ''v2\"\"",
            'DED4' => 'BABA4',
            'DED5' => 'BABA 5',
            'DED6' => 'BABA 	6',
        ]);
    }

    public function testQuotes2()
    {
        $tests = [
            ['Z="\'"\\ "\'"\\ "\'"', "' ' '"],
        ];

        $o = new DotenvParser();
        foreach ($tests as [$source, $result]) {
            self::assertSame($result, $o->parse($source)['Z']);
        }
    }

    public function testMultilines1()
    {
        $result = (new DotenvParser())->parse(<<<'ENV'
        A="

            '12 13'

        "
                B="123
                  
        123"
        
        C='
        
            "1 9"
            
        '
        ENV);

        self::assertSame($result, [
            'A' => "'12 13'",
            'B'  => '123 123',
            'C' => '"1 9"',
        ]);
    }

    public function testMultilines2()
    {
        $ws = "\t";

        $result = (new DotenvParser())->parse(<<<ENV
        A="one, \
        two
        "

        B="one, \

        two
        "
        
        C="one, \\$ws

        two
        "
        
        D='one, \\$ws

        two
        '
        ENV);

        self::assertSame($result, [
            'A' => 'one, two',
            'B' => 'one, two',
            'C' => 'one, \\ two',
            'D' => 'one, \\ two',
        ]);
    }

    public function testMultilinesHighComplexity()
    {
        $result = (new DotenvParser())->parse(<<<'ENV'
            A="123
                  
        123"  F='G'\ '"x'$A	A2='
        1
        
        2
        3
           '	 Y='y'  Z=z\ \Z
        ENV);

        self::assertSame($result, [
            'A'  => '123 123',
            'F'  => 'G "x' . '123 123',
            'A2' => '1 2 3',
            'Y'  => 'y',
            'Z'  => 'z Z',
        ]);
    }

    public function testComments()
    {
        $result = (new DotenvParser())->parse(<<<'ENV'
        DED=1# DED2=BABA2 # my comment
        DED3=BABA3 DED4=BABA4 # DED4=4 # DED5=BABA5
        
        ## more comments #
        # end comment
        ENV);

        self::assertSame($result, [
            'DED' => '1#',
            'DED2' => 'BABA2',
            'DED3' => 'BABA3',
            'DED4' => 'BABA4',
        ]);
    }

    public function testResolving1()
    {
        $result = (new DotenvParser([
            'VALUE' => 99,
        ]))->parse(<<<'ENV'
            K1=test1$VALUE
            K2="test2$VALUE"
            K3='test3$VALUE'
            
            K4=test1$unknown
            K5="test2$unknown"
            K6='test3$unknown'
            
            K7=test1${unknown}
            K8="test2${unknown}"
            K9='test3${unknown}'
            
            X1="test${unknown}"'$K1'$VALUE
            X2="test ${K9}"'$K1'''$VALUE
        ENV);
        self::assertSame($result, [
            'VALUE' => 99,

            'K1' => 'test199',
            'K2' => 'test299',
            'K3' => 'test3$VALUE',

            'K4' => 'test1',
            'K5' => 'test2',
            'K6' => 'test3$unknown',

            'K7' => 'test1',
            'K8' => 'test2',
            'K9' => 'test3${unknown}',

            'X1' => 'test$K199',
            'X2' => 'test test3${unknown}$K199',
        ]);
    }

    public function testResolvingWithDefaultValues()
    {
        $result = (new DotenvParser([
            'VALUE1' => 10,
            'DEFAULT1' => 12,
        ]))->parse(<<<'ENV'

        V1="$VALUE:-"
        V2="$VALUE1:-"
        
        A1="${VALUE1:-${DEFAULT1}}", A2="${VALUE2:-${DEFAULT1}}"
        
        BBB="${TEST_UNKNOWN_VAR:-${DEFAULT1}}"
        X="${a:-${b:-${c:-${d:-99}}}}"
        Y="${a:-${b:-${c:-${BBB}}}}"
        Z="${a:-${b:-${c:-$BBB}}}"
        ENV);

        self::assertSame($result, [
            'VALUE1' => 10,
            'DEFAULT1' => 12,
            'V1'  => ':-',
            'V2'  => '10:-',
            'A1'  => '10,',
            'A2'  => '12',
            'BBB' => '12',
            'X'   => '99',
            'Y'   => '12',
            'Z'   => '12',
        ]);
    }

    public function testInvalidSubstitutionSyntaxException()
    {
        $this->expectExceptionMessage('Invalid substitution');
        (new DotenvParser())->parse(<<<'ENV'
        F_1="${VALUE1:-${DEFAULT1}}", F_2="${${VALUE1}:-${DEFAULT1}}"
        ENV);
    }

    public function testWhitespacesCanNotBeUsedAsDelimitersSyntaxException()
    {
        $this->expectExceptionMessage('line 2. Whitespaces can not be used as delimiters');
        (new DotenvParser())->parse(<<<'ENV'
        t=123
        x= 123
        ENV);
    }

    public function testNoLeftPartSyntaxException1()
    {
        $this->expectExceptionMessage('line 2. No left part');
        (new DotenvParser())->parse(<<<'ENV'
        
        =x=123
        ENV);
    }

    public function testNoLeftPartSyntaxException2()
    {
        $this->expectExceptionMessage('line 1. No left part');
        (new DotenvParser())->parse(<<<'ENV'
        x=\ 1 ==321
        ENV);
    }

    public function testNoLeftPartSyntaxException3()
    {
        $this->expectExceptionMessage('line 3. No left part');
        (new DotenvParser())->parse(<<<'ENV'
        
            x=\ 1
            ==321
        ENV);
    }
}
