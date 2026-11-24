// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data104.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data104 _$Data104FromJson(Map<String, dynamic> json) => Data104(
  uuid: json['uuid'] as String,
  name: json['name'] as String?,
  branchId: (json['branch_id'] as num?)?.toInt(),
  hasApiKey: json['has_api_key'] as bool,
  apiKeyLast4: json['api_key_last4'] as String?,
  apiKeySetAt: json['api_key_set_at'] as String?,
  ready: json['ready'] as bool,
  message: json['message'] as String?,
);

Map<String, dynamic> _$Data104ToJson(Data104 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'name': instance.name,
  'branch_id': instance.branchId,
  'has_api_key': instance.hasApiKey,
  'api_key_last4': instance.apiKeyLast4,
  'api_key_set_at': instance.apiKeySetAt,
  'ready': instance.ready,
  'message': instance.message,
};
