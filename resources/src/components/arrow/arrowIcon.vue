<template>
  <span class="icon-arrow">
    <span class="when-opened">
      <lucide-icon class="dd-arrow" name="arrow-up" />
    </span>
    <span class="when-closed">
      <lucide-icon class="dd-arrow" name="chevron-down" />
    </span>
  </span>
</template>
<style>
.collapsed > a .when-opened,
:not(.collapsed) > a .when-closed {
  display: none;
}
</style>