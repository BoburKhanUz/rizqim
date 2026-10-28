// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_customer_accounts_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosCustomerAccountsUuidResponse
_$GetV1PosCustomerAccountsUuidResponseFromJson(Map<String, dynamic> json) =>
    GetV1PosCustomerAccountsUuidResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data144.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1PosCustomerAccountsUuidResponseToJson(
  GetV1PosCustomerAccountsUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
