<?php

namespace SpellChecker;

class SpellChecker
{

    /** @var \SpellChecker\WordsParser */
    private $wordsParser;

    /** @var \SpellChecker\DictionaryResolver */
    private $resolver;

    /** @var \SpellChecker\DictionaryCollection */
    private $dictionaries;

    public function __construct(
        WordsParser $wordsParser,
        DictionaryResolver $resolver,
        DictionaryCollection $dictionaries
    )
    {
        $this->wordsParser = $wordsParser;
        $this->resolver = $resolver;
        $this->dictionaries = $dictionaries;
    }

    /**
     * @param string[] $paths
     * @return \SpellChecker\Word[][]
     */
    public function checkDirectories(array $paths): array
    {
        $errors = [];
        foreach ($paths as $path) {
            $errors = array_merge($errors, $this->checkDirectory($path));
        }

        return $errors;
    }

    /**
     * @param string $path
     * @return \SpellChecker\Word[][]
     */
    private function checkDirectory(string $path): array
    {
        $errors = [];
        foreach (glob($path . '/*') as $file) {
            if (is_dir($file)) {
                $dirErrors = $this->checkDirectory($file);
                if ($dirErrors !== []) {
                    foreach ($dirErrors as $error) {
                        $dirErrors[] = $error;
                    }
                }
            } elseif (!is_readable($file)) {
                continue;
            }
            $dictionaries = $this->resolver->getDictionariesForFileName($file);
            if ($dictionaries === []) {
                continue;
            }
            $fileErrors = $this->checkFile($file, $dictionaries);
            if ($fileErrors !== []) {
                $errors[$file . ' (' . implode(', ', $dictionaries) . ')'] = $fileErrors;
            }
        }

        return $errors;
    }

    /**
     * @param string[] $files
     * @return \SpellChecker\Word[][]
     */
    public function checkFiles(array $files): array
    {
        $errors = [];
        foreach ($files as $file) {
            if (!is_readable($file)) {
                continue;
            }
            $dictionaries = $this->resolver->getDictionariesForFileName($file);
            if ($dictionaries === []) {
                continue;
            }
            $fileErrors = $this->checkFile($file, $dictionaries);
            if ($fileErrors !== []) {
                $errors[$file . ' (' . implode(', ', $dictionaries) . ')'] = $fileErrors;
            }
        }

        return $errors;
    }

    /**
     * @param string $fileName
     * @param string[] $dictionaries
     * @return \SpellChecker\Word[]
     */
    private function checkFile(string $fileName, array $dictionaries): array
    {
        ///
        $string = file_get_contents($fileName);
        $string = \Nette\Utils\Strings::normalize($string);
        ///

        return $this->checkString($string, $dictionaries, $fileName);
    }

    /**
     * @param string $string
     * @param string[] $dictionaries
     * @param string $fileName
     * @return \SpellChecker\Word[]
     */
    public function checkString(string $string, array $dictionaries, string $fileName): array
    {
        $errors = [];
        foreach ($this->wordsParser->parse($string) as $word) {
            if ($this->dictionaries->contains($word->word, $dictionaries)) {
                continue;
            }
            if ($this->dictionaries->contains(mb_strtolower($word->word), $dictionaries)) {
                continue;
            }
            if ($word->block !== null && $this->dictionaries->contains($word->block, $dictionaries)) {
                continue;
            }

            $trimmed = $this->trimNumbersFromRight($word->word);
            if ($trimmed !== null) {
                if ($this->dictionaries->contains($trimmed, $dictionaries)) {
                    continue;
                }
                if ($this->dictionaries->contains(mb_strtolower($trimmed), $dictionaries)) {
                    continue;
                }
            }
            if ($word->looksLikeToken()) {
                continue;
            }

            $context = substr($string, $word->position - 30, strlen($word->word) + 60);
            while (ord($context[0]) & 0b11000000 === 0b1000000) {
                $context = substr($context, 1);
            }
            $word->context = '…' . preg_replace('/([ ]{2,}|\\t+)/', '→', str_replace("\n", '↓', $context)) . '…';
            $errors[] = $word;
        }

        return $errors;
    }

    private function trimNumbersFromRight(string $word): ?string
    {
        if (preg_match('/[0-9]+$/', $word, $match)) {
            return substr($word, 0, -strlen($match[0]));
        } else {
            return null;
        }
    }

}
