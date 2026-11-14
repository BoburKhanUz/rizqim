// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_pos_vouchers_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerPosVouchersResponse _$GetV1OwnerPosVouchersResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerPosVouchersResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data167.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerPosVouchersResponseToJson(
  GetV1OwnerPosVouchersResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
