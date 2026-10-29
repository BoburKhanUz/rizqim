// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_sync_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosSyncRequestBody _$V1PosSyncRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosSyncRequestBody(
  operations: (json['operations'] as List<dynamic>)
      .map((e) => Operations.fromJson(e as Map<String, dynamic>))
      .toList(),
  sentAt: json['sent_at'] == null
      ? null
      : DateTime.parse(json['sent_at'] as String),
  queue: json['queue'] == null
      ? null
      : Queue19.fromJson(json['queue'] as Map<String, dynamic>),
);

Map<String, dynamic> _$V1PosSyncRequestBodyToJson(
  V1PosSyncRequestBody instance,
) => <String, dynamic>{
  'sent_at': instance.sentAt?.toIso8601String(),
  'queue': instance.queue,
  'operations': instance.operations,
};
