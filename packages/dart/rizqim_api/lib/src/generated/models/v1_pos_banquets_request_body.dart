// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'banquet_slot.dart';
import 'banquet_event_type.dart';
import 'extras2.dart';

part 'v1_pos_banquets_request_body.g.dart';

@JsonSerializable()
class V1PosBanquetsRequestBody {
  const V1PosBanquetsRequestBody({
    required this.eventDate,
    required this.slot,
    required this.hallIds,
    required this.customerName,
    required this.guestsGuaranteed,
    this.branchId,
    this.startTime,
    this.endTime,
    this.eventType,
    this.title,
    this.customerPhone,
    this.guestsMinimum,
    this.banquetPackageId,
    this.extras,
    this.quoteCurrency,
    this.quoteRate,
    this.note,
  });

  factory V1PosBanquetsRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1PosBanquetsRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;
  @JsonKey(name: 'event_date')
  final DateTime eventDate;
  final BanquetSlot slot;
  @JsonKey(name: 'start_time')
  final String? startTime;
  @JsonKey(name: 'end_time')
  final String? endTime;
  @JsonKey(name: 'hall_ids')
  final List<int> hallIds;
  @JsonKey(name: 'event_type')
  final BanquetEventType? eventType;
  final String? title;
  @JsonKey(name: 'customer_name')
  final String customerName;
  @JsonKey(name: 'customer_phone')
  final String? customerPhone;
  @JsonKey(name: 'guests_guaranteed')
  final int guestsGuaranteed;
  @JsonKey(name: 'guests_minimum')
  final int? guestsMinimum;
  @JsonKey(name: 'banquet_package_id')
  final int? banquetPackageId;
  final List<Extras2>? extras;
  @JsonKey(name: 'quote_currency')
  final String? quoteCurrency;
  @JsonKey(name: 'quote_rate')
  final num? quoteRate;
  final String? note;

  Map<String, Object?> toJson() => _$V1PosBanquetsRequestBodyToJson(this);
}
