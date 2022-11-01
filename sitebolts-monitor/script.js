document.addEventListener('DOMContentLoaded', function()
{
	function sbmon_get_random_string(allowed_characters, string_length)
	{
		let result = '';
		
		for (let i = 0; i < string_length; i++)
		{
			result += allowed_characters[(Math.floor(Math.random() * allowed_characters.length))];
		}
		
		return result;
	}
	
	document.querySelectorAll('.sitebolts-monitor-generate-token-button').forEach(function(event_element)
	{
		event_element.addEventListener('click', function(event)
		{
			let token_input_element = document.querySelector('.sitebolts-monitor-token-input');
			let token = sbmon_get_random_string('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890!@#$%^&*()-_+=', 32);
			
			token_input_element.value = token;
		});
	});
	
	document.querySelectorAll('.sitebolts-monitor-save-token-button').forEach(function(event_element)
	{
		event_element.addEventListener('click', function(event)
		{
			let token_input_element = document.querySelector('.sitebolts-monitor-token-input');
			let token_feedback_element = document.querySelector('.sitebolts-monitor-token-feedback');
			
			let token = token_input_element.value;
			
			let form_data = new FormData();
			form_data.append('action', 'sbmon_save_new_token_ajax');
			form_data.append('token', token);
			
			fetch(sbmon_globals.ajax_url, {method: 'POST', headers: {}, body: form_data})
			.then
			(
				response => response.json()
			)
			.then
			(
				data =>
				{
					console.log(data);
					
					if (data.status === 'success')
					{
						token_feedback_element.innerHTML = data.html_message;
					}
					
					else if (data.status === 'error')
					{
						token_feedback_element.innerHTML = data.html_message;
					}
					
					else
					{
						throw 'Unexpected response status';
					}
				}
			)
			.catch(error =>
			{
				token_feedback_element.innerHTML = '<p class="error-message">An unknown error occured.</p>';
			});
		});
	});
});