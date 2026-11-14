// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'event5.g.dart';

@JsonSerializable()
class Event5 {
  const Event5({
    required this.uuid,
    required this.status,
    required this.statusLabel,
    required this.eventDate,
    required this.total,
    required this.paidTotal,
    required this.version,
  });

  factory Event5.fromJson(Map<String, Object?> json) => _$Event5FromJson(json);

  final String uuid;
  final String status;
  @JsonKey(name: 'status_label')
  final String statusLabel;
  @JsonKey(name: 'event_date')
  final String eventDate;
  final String total;
  @JsonKey(name: 'paid_total')
  final String paidTotal;
  final int version;

  Map<String, Object?> toJson() => _$Event5ToJson(this);
}
