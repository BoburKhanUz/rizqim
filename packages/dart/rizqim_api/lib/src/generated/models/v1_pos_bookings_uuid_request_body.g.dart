// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_bookings_uuid_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosBookingsUuidRequestBody _$V1PosBookingsUuidRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosBookingsUuidRequestBody(
  diningTableId: (json['dining_table_id'] as num?)?.toInt(),
  extraTableIds: (json['extra_table_ids'] as List<dynamic>?)
      ?.map((e) => (e as num).toInt())
      .toList(),
  startsAt: json['starts_at'] == null
      ? null
      : DateTime.parse(json['starts_at'] as String),
  endsAt: json['ends_at'] == null
      ? null
      : DateTime.parse(json['ends_at'] as String),
  guestsPlanned: (json['guests_planned'] as num?)?.toInt(),
  eventType: json['event_type'] == null
      ? null
      : EventType.fromJson(json['event_type'] as String?),
  contactName: json['contact_name'] as String?,
  contactPhone: json['contact_phone'] as String?,
  source: json['source'] == null
      ? null
      : Source2.fromJson(json['source'] as String?),
  minSpend: json['min_spend'] as num?,
  note: json['note'] as String?,
  version: (json['version'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1PosBookingsUuidRequestBodyToJson(
  V1PosBookingsUuidRequestBody instance,
) => <String, dynamic>{
  'dining_table_id': instance.diningTableId,
  'extra_table_ids': instance.extraTableIds,
  'starts_at': instance.startsAt?.toIso8601String(),
  'ends_at': instance.endsAt?.toIso8601String(),
  'guests_planned': instance.guestsPlanned,
  'event_type': instance.eventType,
  'contact_name': instance.contactName,
  'contact_phone': instance.contactPhone,
  'source': instance.source,
  'min_spend': instance.minSpend,
  'note': instance.note,
  'version': instance.version,
};
