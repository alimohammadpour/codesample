import { Effect, all, call, put, takeLatest } from 'redux-saga/effects';
import { 
    getAttacksSaga, 
    getAttacks, 
    fetchGetAttacksRequestSaga 
} from './attacksSaga';
import { 
    AttacksPagination, 
    AttacksQueryParams, 
    FetchGetAttacksFailurePayload, 
    FetchGetAttacksRequest, 
    FetchGetAttacksRequestPayload, 
    FetchGetAttacksSuccessPayload 
} from '../actions/types/attacksTypes';
import { FETCH_GET_ATTACKS_REQUEST } from '../actions/enums/attacksTypes';
import { fetchGetAttacksFailure, fetchGetAttacksSuccess } from '../actions/attacksAction';
import { ErrorHandler } from '../classes/ErrorHandler';

describe('attacksSaga', () => {
    it('should be defined', () => expect(getAttacksSaga).toBeDefined());

    it('get attacks saga', () => {
        const generator: Generator = getAttacksSaga();
        
        const attacksRequestTakeLatestEffect: Effect = takeLatest(FETCH_GET_ATTACKS_REQUEST, fetchGetAttacksRequestSaga);

        const allEffects: Effect = all([attacksRequestTakeLatestEffect]);

        expect(generator.next().value).toEqual(allEffects);
        expect(generator.next()).toEqual({ done: true, value: undefined });
    })

    describe('fetch get attacks request saga', () => {
        const queryParams: AttacksQueryParams = { 
            searchField: { field: null, value: '' }, 
            page: 1, 
            limit: 1 
        };
        const payload: FetchGetAttacksRequestPayload = { queryParams };
        const action: FetchGetAttacksRequest = {
            type: FETCH_GET_ATTACKS_REQUEST,
            payload
        }
        const generator: Generator = fetchGetAttacksRequestSaga(action);

        it('try', () => {
            const attacks: AttacksPagination = { entities: [], totalCount: 0 };
            const attacksPayload: FetchGetAttacksSuccessPayload = { attacks };
            const apiRetunedAttacks: { data: AttacksPagination } = { data: attacks };

            const attacksCallEffect: Effect = call(getAttacks, queryParams);
            const attacksPutSuccessEffect: Effect = put(fetchGetAttacksSuccess(attacksPayload))
    
            expect(generator.next().value).toEqual(attacksCallEffect);
    
            expect(generator.next(apiRetunedAttacks).value).toEqual(attacksPutSuccessEffect);
        })

        it('catch', () => {
            jest.spyOn(ErrorHandler, 'getMessage').mockImplementation(() => '');

            const errorPayload: FetchGetAttacksFailurePayload = { error: '' };
            const attacksPutFailureEffect: Effect = put(fetchGetAttacksFailure(errorPayload));

            expect(generator.throw(undefined).value).toEqual(attacksPutFailureEffect);
            expect(generator.next()).toEqual({ done: true, value: undefined });
        })
    })
})
