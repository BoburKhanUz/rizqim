// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_kds_targets_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerKdsTargetsResponse _$GetV1OwnerKdsTargetsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerKdsTargetsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data105.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerKdsTargetsResponseToJson(
  GetV1OwnerKdsTargetsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
