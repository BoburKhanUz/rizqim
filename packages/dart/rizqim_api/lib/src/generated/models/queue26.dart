// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'pickup_status.dart';

part 'queue26.g.dart';

@JsonSerializable()
class Queue26 {
  const Queue26({
    required this.number,
    required this.businessDate,
    required this.status,
    required this.readyAt,
    required this.calledAt,
    required this.callCount,
    required this.handedAt,
  });

  factory Queue26.fromJson(Map<String, Object?> json) =>
      _$Queue26FromJson(json);

  final int number;
  @JsonKey(name: 'business_date')
  final String? businessDate;
  final PickupStatus? status;
  @JsonKey(name: 'ready_at')
  final String? readyAt;
  @JsonKey(name: 'called_at')
  final String? calledAt;
  @JsonKey(name: 'call_count')
  final int callCount;
  @JsonKey(name: 'handed_at')
  final String? handedAt;

  Map<String, Object?> toJson() => _$Queue26ToJson(this);
}
