// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_kds_expo_scope_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerKdsExpoScopeResponse _$PutV1OwnerKdsExpoScopeResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerKdsExpoScopeResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data111.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerKdsExpoScopeResponseToJson(
  PutV1OwnerKdsExpoScopeResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
