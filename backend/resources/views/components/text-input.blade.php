@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'border-gray-300 dark:border-gray-700 bg-white dark:bg-[#121212] text-gray-900 dark:text-gray-300 focus:border-[#5CC98B] dark:focus:border-[#5CC98B] focus:ring-[#5CC98B] dark:focus:ring-[#5CC98B] rounded-md shadow-sm transition-colors duration-200']) !!}>