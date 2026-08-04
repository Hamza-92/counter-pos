@if(session('status'))<div class="flash">{{ session('status') }}</div>@endif
@if($errors->any())<div class="errors"><strong>Please correct the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
