<?php

if (!defined('ABSPATH')) {
    exit;
}

class SOF_ViewerCapabilitySet
{
    public bool $responsive = false;

    public bool $fullscreen = false;

    public bool $thumbnail_navigation = false;

    public bool $search = false;

    public bool $background_themes = false;

    public bool $mobile_gestures = false;

    public bool $custom_toolbar = false;
}