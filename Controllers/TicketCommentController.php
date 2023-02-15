<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Enums\LogTypeEnum;
use App\Events\UserLogActionEvent;
use App\Exceptions\MethodNotAllowedException;
use App\Http\Controllers\ApiController;
use App\Http\Requests\CreateTicketReplyRequest;
use App\Http\Requests\EditTicketReplyRequest;
use App\Interfaces\Ticket\TicketRepositoryInterface;
use App\Interfaces\Ticket\TicketCommentRepositoryInterface;
use App\Transformers\Ticketing\TicketCommentTransformer;

class TicketCommentController extends ApiController
{

    private $ticketRepository, $ticketCommentRepository;

    public function __construct(TicketRepositoryInterface $ticketRepository, TicketCommentRepositoryInterface $ticketCommentRepository)
    {
        parent::__construct();
        $this->ticketRepository = $ticketRepository;
        $this->ticketCommentRepository = $ticketCommentRepository;
    }

    /**
     * @param $ticketId
     *
     * @return \Dingo\Api\Http\Response
     */
    public function index($ticketId)
    {
        $ticket = $this->ticketRepository->findOrFail($ticketId);
        if ($this->user->can('view', $ticket)) {
            $comments = $this->paginate($this->ticketCommentRepository->get($ticketId));
            return $this->response->paginator($comments, new TicketCommentTransformer());
        } else
            throw new MethodNotAllowedException();
    }

    /**
     * @param \App\Http\Requests\CreateTicketReplyRequest $request
     *
     * @return \Dingo\Api\Http\Response
     */
    public function store(CreateTicketReplyRequest $request)
    {
        $ticket = $this->ticketRepository->findOrFail($request->ticket_id);
        if ($this->user->can('reply', $ticket)) {
            $comment = $this->ticketCommentRepository->create([
                $request->html, 
                strip_tags($request->html), 
                $this->user->id, 
                $request->ticket_id
            ]);
            event(new UserLogActionEvent(LogTypeEnum::TICKET_COMMENT_REPLYED, $this->user, $ticket->subject));
            return $this->response->item($comment, new TicketCommentTransformer());
        } else {
            throw new MethodNotAllowedException();
        }

    }

    /**
     * @param $commentId
     *
     * @return \Dingo\Api\Http\Response
     * @throws \Exception
     */
    public function destroy($commentId)
    {
        $comment = $this->ticketCommentRepository->find($commentId);
        if ($this->user->can('delete', $comment)) {
            $this->ticketCommentRepository->destroy($commentId);
            event(new UserLogActionEvent(LogTypeEnum::TICKET_COMMENT_DELETED, $this->user, $comment->id));
            return $this->response->item($comment, new TicketCommentTransformer());
        } else {
            throw new MethodNotAllowedException();
        }

    }

    /**
     * @param \App\Http\Requests\EditTicketReplyRequest $request
     *
     * @return \Dingo\Api\Http\Response
     */
    public function update(EditTicketReplyRequest $request)
    {
        $comment = $this->ticketCommentRepository->find($request->id);
        if ($this->user->can('update', $comment)) {
            $this->ticketCommentRepository->update($request->id, $request->all());
            event(new UserLogActionEvent(LogTypeEnum::TICKET_COMMENT_UPDATED, $this->user, $comment->id));
            return $this->response->item($comment, new TicketCommentTransformer());
        } else 
            throw new MethodNotAllowedException();
    }
}
