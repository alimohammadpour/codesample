import { attacksReducer } from "./attacksReducer"
import { 
    FetchGetAttacksRequest, 
    FetchGetAttacksRequestPayload, 
    AttacksQueryParams, 
    FetchGetAttacksSuccess, 
    FetchGetAttacksSuccessPayload, 
    AttacksPagination, 
    FetchGetAttacksFailure, 
    FetchGetAttacksFailurePayload 
} from '../actions/types/attacksTypes';
import { 
    FETCH_GET_ATTACKS_REQUEST, 
    FETCH_GET_ATTACKS_SUCCESS, 
    FETCH_GET_ATTACKS_FAILURE 
} from '../actions/enums/attacksTypes';

describe('attacksReducer', () => {
    it('should be defined', () => expect(attacksReducer).toBeDefined())

    it('fetch get attacks request', () => {
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

        const requestState = attacksReducer(undefined, action);

        expect(requestState).toEqual({ pending: true });
    })

    it('fetch get attacks success', () => {
        const attacks: AttacksPagination = { entities: [], totalCount: 0 };
        const payload: FetchGetAttacksSuccessPayload = { attacks };
        const action: FetchGetAttacksSuccess = {
            type: FETCH_GET_ATTACKS_SUCCESS,
            payload
        }

        const successState = attacksReducer(undefined, action)

        expect(successState).toEqual({ pending: false, attacks })
    })

    it('fetch get attacks failure', () => {
        const payload: FetchGetAttacksFailurePayload = { error: '' };
        const action: FetchGetAttacksFailure = {
            type: FETCH_GET_ATTACKS_FAILURE,
            payload
        }

        const failureState = attacksReducer(undefined, action);

        expect(failureState).toEqual({ pending: false, error: payload.error });
    })
})
