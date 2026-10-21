// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_payments_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerPaymentsResponse _$GetV1OwnerPaymentsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerPaymentsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data57.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerPaymentsResponseToJson(
  GetV1OwnerPaymentsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
