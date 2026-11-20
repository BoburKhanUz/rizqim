// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'unsigned_tills2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

UnsignedTills2 _$UnsignedTills2FromJson(Map<String, dynamic> json) =>
    UnsignedTills2(
      uuid: json['uuid'] as String,
      name: json['name'] as String,
      branchId: (json['branch_id'] as num?)?.toInt(),
      hasKey: json['has_key'] as bool,
      lastSigStatus: json['last_sig_status'] as String?,
    );

Map<String, dynamic> _$UnsignedTills2ToJson(UnsignedTills2 instance) =>
    <String, dynamic>{
      'uuid': instance.uuid,
      'name': instance.name,
      'branch_id': instance.branchId,
      'has_key': instance.hasKey,
      'last_sig_status': instance.lastSigStatus,
    };
