<?php

namespace up2top\FlatFiles\Console;

trait Messagable
{
    protected $errors = [];
    protected $warnings = [];
    protected $prompts = [];

    /**
     * Add warning message.
     */
    public function addWarning($message)
    {
        $this->warnings[] = $message;
    }

    /**
     * Add prompt message.
     */
    public function addPrompt($message)
    {
        $this->prompts[] = $message;
    }

    /**
     * Add error message.
     */
    public function addError($message)
    {
        $this->errors[] = $message;
    }

    /**
     * Show console messages: warnings, prompts and errors.
     */
    public function showMessages()
    {
        foreach ($this->warnings as $warning) {
            $this->comment($warning);
        }

        foreach ($this->prompts as $prompt) {
            $this->question($prompt);
        }

        foreach ($this->errors as $error) {
            $this->error($error);
        }
    }

    /**
     * Is stop required due to errors or prompts?
     */
    public function isStopRequired()
    {
        if (! empty($this->errors)) {
            return true;
        }

        if (! empty($this->prompts) && ! $this->confirm('Continue anyway?')) {
            return true;
        }

        return false;
    }
}
