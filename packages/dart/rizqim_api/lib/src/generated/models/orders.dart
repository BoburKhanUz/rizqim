// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'orders.g.dart';

@JsonSerializable()
class Orders {
  const Orders({
    required this.count,
    required this.medianSeconds,
    required this.p90Seconds,
    required this.withTarget,
    required this.overTarget,
    required this.overTargetShare,
  });

  factory Orders.fromJson(Map<String, Object?> json) => _$OrdersFromJson(json);

  final int count;
  @JsonKey(name: 'median_seconds')
  final int? medianSeconds;
  @JsonKey(name: 'p90_seconds')
  final int? p90Seconds;
  @JsonKey(name: 'with_target')
  final int withTarget;
  @JsonKey(name: 'over_target')
  final int overTarget;
  @JsonKey(name: 'over_target_share')
  final String? overTargetShare;

  Map<String, Object?> toJson() => _$OrdersToJson(this);
}
