# famous-smoke.com parsing tool

## Project requirements:
[composer](https://getcomposer.org/download/)
[hunspell](http://hunspell.github.io) with en dictionary
In *nix systems pages are cold: hunspell & hunspell-en-us

## To build & compile run (after changes in code):
```
composer install
php bin/compile
```
In root project directory will be generated parser.phar file which is compiled version of the console application

## Run parser:
```
php parser.phar parse --with-spellcheck --dict-path[=DICT-PATH]

--with-spellcheck        Run parsing with spell check for description fields
--dict-path[=DICT-PATH]  Txt file with stopwords for spellcheck. 
                         Single word on file row (see stopwords_example.txt in project root for example)
```
In results will be generated: yymmddHHMMSS.xlsx file
You can run code without compilation (after changes for example):
```
php bin/parser parse --with-spellcheck --dict-path[=DICT-PATH]
```

## Run diff tool:
```
php diff.phar diff

```
In results will be generated: changes.xlsx file
You can run code without compilation (after changes for example):
```
php bin/diff diff
```
