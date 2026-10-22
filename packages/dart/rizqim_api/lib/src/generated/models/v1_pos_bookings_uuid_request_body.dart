// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'event_type.dart';
import 'source.dart';

part 'v1_pos_bookings_uuid_request_body.g.dart';

@JsonSerializable()
class V1PosBookingsUuidRequestBody {
  const V1PosBookingsUuidRequestBody({
    this.diningTableId,
    this.extraTableIds,
    this.startsAt,
    this.endsAt,
    this.guestsPlanned,
    this.eventType,
    this.contactName,
    this.contactPhone,
    this.source,
    this.minSpend,
    this.note,
    this.version,
  });

  factory V1PosBookingsUuidRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1PosBookingsUuidRequestBodyFromJson(json);

  @JsonKey(name: 'dining_table_id')
  final int? diningTableId;
  @JsonKey(name: 'extra_table_ids')
  final List<int>? extraTableIds;
  @JsonKey(name: 'starts_at')
  final DateTime? startsAt;
  @JsonKey(name: 'ends_at')
  final DateTime? endsAt;
  @JsonKey(name: 'guests_planned')
  final int? guestsPlanned;
  @JsonKey(name: 'event_type')
  final EventType? eventType;
  @JsonKey(name: 'contact_name')
  final String? contactName;
  @JsonKey(name: 'contact_phone')
  final String? contactPhone;
  final Source? source;
  @JsonKey(name: 'min_spend')
  final num? minSpend;
  final String? note;
  final int? version;

  Map<String, Object?> toJson() => _$V1PosBookingsUuidRequestBodyToJson(this);
}
