// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_billing_entitlements_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerBillingEntitlementsResponse
_$GetV1OwnerBillingEntitlementsResponseFromJson(Map<String, dynamic> json) =>
    GetV1OwnerBillingEntitlementsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data35.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1OwnerBillingEntitlementsResponseToJson(
  GetV1OwnerBillingEntitlementsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
