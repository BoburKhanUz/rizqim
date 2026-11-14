// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_banquets_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosBanquetsRequestBody _$V1PosBanquetsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosBanquetsRequestBody(
  eventDate: DateTime.parse(json['event_date'] as String),
  slot: BanquetSlot.fromJson(json['slot'] as String),
  hallIds: (json['hall_ids'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
  customerName: json['customer_name'] as String,
  guestsGuaranteed: (json['guests_guaranteed'] as num).toInt(),
  branchId: (json['branch_id'] as num?)?.toInt(),
  startTime: json['start_time'] as String?,
  endTime: json['end_time'] as String?,
  eventType: json['event_type'] == null
      ? null
      : BanquetEventType.fromJson(json['event_type'] as String),
  title: json['title'] as String?,
  customerPhone: json['customer_phone'] as String?,
  guestsMinimum: (json['guests_minimum'] as num?)?.toInt(),
  banquetPackageId: (json['banquet_package_id'] as num?)?.toInt(),
  extras: (json['extras'] as List<dynamic>?)
      ?.map((e) => Extras2.fromJson(e as Map<String, dynamic>))
      .toList(),
  quoteCurrency: json['quote_currency'] as String?,
  quoteRate: json['quote_rate'] as num?,
  note: json['note'] as String?,
);

Map<String, dynamic> _$V1PosBanquetsRequestBodyToJson(
  V1PosBanquetsRequestBody instance,
) => <String, dynamic>{
  'branch_id': instance.branchId,
  'event_date': instance.eventDate.toIso8601String(),
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
};
