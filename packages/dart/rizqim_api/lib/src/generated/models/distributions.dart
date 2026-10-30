// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'distributions.g.dart';

@JsonSerializable()
class Distributions {
  const Distributions({
    required this.uuid,
    required this.shiftUuid,
    required this.status,
    required this.poolAmount,
    required this.distributed,
  });

  factory Distributions.fromJson(Map<String, Object?> json) =>
      _$DistributionsFromJson(json);

  final String uuid;
  @JsonKey(name: 'shift_uuid')
  final String? shiftUuid;
  final String status;
  @JsonKey(name: 'pool_amount')
  final String poolAmount;
  final String distributed;

  Map<String, Object?> toJson() => _$DistributionsToJson(this);
}
