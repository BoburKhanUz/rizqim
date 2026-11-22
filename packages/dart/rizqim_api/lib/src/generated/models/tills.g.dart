// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'tills.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Tills _$TillsFromJson(Map<String, dynamic> json) => Tills(
  uuid: json['uuid'] as String,
  name: json['name'] as String?,
  branchId: (json['branch_id'] as num?)?.toInt(),
  hasApiKey: json['has_api_key'] as bool,
  apiKeyLast4: json['api_key_last4'] as String?,
  apiKeySetAt: json['api_key_set_at'] as String?,
  ready: json['ready'] as bool,
  message: json['message'] as String?,
);

Map<String, dynamic> _$TillsToJson(Tills instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'name': instance.name,
  'branch_id': instance.branchId,
  'has_api_key': instance.hasApiKey,
  'api_key_last4': instance.apiKeyLast4,
  'api_key_set_at': instance.apiKeySetAt,
  'ready': instance.ready,
  'message': instance.message,
};
