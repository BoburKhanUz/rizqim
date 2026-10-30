// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'distributions.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Distributions _$DistributionsFromJson(Map<String, dynamic> json) =>
    Distributions(
      uuid: json['uuid'] as String,
      shiftUuid: json['shift_uuid'] as String?,
      status: json['status'] as String,
      poolAmount: json['pool_amount'] as String,
      distributed: json['distributed'] as String,
    );

Map<String, dynamic> _$DistributionsToJson(Distributions instance) =>
    <String, dynamic>{
      'uuid': instance.uuid,
      'shift_uuid': instance.shiftUuid,
      'status': instance.status,
      'pool_amount': instance.poolAmount,
      'distributed': instance.distributed,
    };
