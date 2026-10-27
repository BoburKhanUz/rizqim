// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_payouts_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerPayoutsResponse _$GetV1OwnerPayoutsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerPayoutsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data108.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerPayoutsResponseToJson(
  GetV1OwnerPayoutsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
