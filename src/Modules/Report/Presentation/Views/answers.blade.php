<table class="answers">
    <thead>
        <tr>
            <th class="question">Item verificado</th>
            @foreach($answerOptions as $option)
                <th class="answer">{{ $option }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($answers as $question => $answer)
            <tr>
                <td>{{ $question }}</td>
                @foreach($answerOptions as $option)
                    <td class="answer">{{ $answer === $option ? 'X' : '' }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
