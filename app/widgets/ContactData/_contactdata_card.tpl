{$url = null}
{$url = $contact->getPhoto('l')}
<ul class="list">
    <li class="block large">
        <div>
            <p class="center all">
                {if="$url"}
                    <img class="avatar" src="{$url}"/>
                {else}
                    <span class="avatar icon color {$contact->jid|stringToColor}">
                        {$contact->jid|firstLetterCapitalize}
                    </span>
                {/if}
            </p>
        </div>
    </li>
</ul>

<ul class="list thick">
    <li>
        <div>
            <p class="normal center	">
                {$contact->truename}
                {if="isset($roster) && isset($roster->presence)"}
                    <span class="second">{$roster->presence->presencetext}</span>
                {/if}
            </p>
            <p class="all center">
                {if="$contact->description != null && trim($contact->description) != ''"}
                    {autoescape="off"}
                        {$contact->description|trim|nl2br|addEmojis}
                    {/autoescape}
                    <br /><br />
                {/if}

                {if="$roster && $roster->presence && $roster->presence->seen"}
                    <i class="material-icons icon-text">schedule</i>
                    {$c->__('last.title')} {$roster->presence->seen|strtotime|prepareDate:true,true}
                    <br />
                {/if}

                {if="$contact->adrlocality != null || $contact->adrcountry != null"}
                    <i class="material-icons icon-text">place</i>
                    {if="$contact->adrlocality != null"}
                        {$contact->adrlocality}
                    {/if}
                    {if="$contact->adrcountry != null"}
                        {$contact->adrcountry}
                    {/if}
                    <br />
                {/if}

                {if="$contact->email"}
                    <i class="material-icons icon-text">email</i>
                    <a href="mailto:{$contact->email}">{$contact->email}</a>
                    <br />
                {/if}

                {if="$contact->phone"}
                    <i class="material-icons icon-text">phone</i>
                    <a href="tel:{$contact->phone}">{$contact->phone}</a>
                    <br />
                {/if}

                {if="$contact->url != null"}
                    <i class="material-icons icon-text">link</i>
                    {if="filter_var($contact->url, FILTER_VALIDATE_URL)"}
                        <a href="{$contact->url}" target="_blank">{$contact->url}</a>
                    {else}
                        {$contact->url}
                    {/if}
                    <br />
                {/if}

                {if="$contact->locationDistance != null && $contact->locationUrl != null"}
                    <i class="material-icons icon-text">place</i>
                    <a href="{$contact->locationUrl}" target="_blank">{$contact->locationDistance|humanDistance}</a> - {$contact->loctimestamp|strtotime|prepareDate:true,true}
                    <br />
                {elseif="$contact->hasLocation() && $contact->locationUrl != null"}
                    <i class="material-icons icon-text">place</i>
                    <a href="{$contact->locationUrl}" target="_blank">{$c->__('location.last_published')}</a> - {$contact->loctimestamp|strtotime|prepareDate:true,true}
                    <br />
                {/if}
            </p>
        </div>
    </li>
    <!--<li>
        <span class="primary icon gray">
            <i class="material-icons">accounts</i>
        </span>
        <p class="normal">{$c->__('communitydata.sub', 0)}</p>
    </li>-->
</ul>
<br />