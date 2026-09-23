{{-- `pages/_error.js`: any other status, shown with its code. --}}
<x-error-page :status="$exception->getStatusCode()" />
