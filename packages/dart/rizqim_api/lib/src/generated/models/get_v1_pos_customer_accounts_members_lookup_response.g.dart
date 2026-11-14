// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_customer_accounts_members_lookup_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosCustomerAccountsMembersLookupResponse
_$GetV1PosCustomerAccountsMembersLookupResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosCustomerAccountsMembersLookupResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data278.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosCustomerAccountsMembersLookupResponseToJson(
  GetV1PosCustomerAccountsMembersLookupResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
