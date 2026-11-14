// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_banquets_uuid_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosBanquetsUuidRequestBody _$V1PosBanquetsUuidRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosBanquetsUuidRequestBody(
  eventDate: json['event_date'] == null
      ? null
      : DateTime.parse(json['event_date'] as String),
  slot: json['slot'] == null
      ? null
      : BanquetSlot.fromJson(json['slot'] as String),
  startTime: json['start_time'] as String?,
  endTime: json['end_time'] as String?,
  hallIds: (json['hall_ids'] as List<dynamic>?)
      ?.map((e) => (e as num).toInt())
      .toList(),
  eventType: json['event_type'] == null
      ? null
      : BanquetEventType.fromJson(json['event_type'] as String),
  title: json['title'] as String?,
  customerName: json['customer_name'] as String?,
  customerPhone: json['customer_phone'] as String?,
  guestsGuaranteed: (json['guests_guaranteed'] as num?)?.toInt(),
  guestsMinimum: (json['guests_minimum'] as num?)?.toInt(),
  banquetPackageId: (json['banquet_package_id'] as num?)?.toInt(),
  extras: (json['extras'] as List<dynamic>?)
      ?.map((e) => Extras4.fromJson(e as Map<String, dynamic>))
      .toList(),
  quoteCurrency: json['quote_currency'] as String?,
  quoteRate: json['quote_rate'] as num?,
  note: json['note'] as String?,
  version: (json['version'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1PosBanquetsUuidRequestBodyToJson(
  V1PosBanquetsUuidRequestBody instance,
) => <String, dynamic>{
  'event_date': instance.eventDate?.toIso8601String(),
  'slot': instance.slot,
  'start_time': instance.startTime,
  'end_time': instance.endTime,
  'hall_ids': instance.hallIds,
  'event_type': instance.eventType,
  'title': instance.title,
  'customer_name': instance.customerName,
  'customer_phone': instance.customerPhone,
  'guests_guaranteed': instance.guestsGuaranteed,
  'guests_minimum': instance.guestsMinimum,
  'banquet_package_id': instance.banquetPackageId,
  'extras': instance.extras,
  'quote_currency': instance.quoteCurrency,
  'quote_rate': instance.quoteRate,
  'note': instance.note,
  'version': instance.version,
};
