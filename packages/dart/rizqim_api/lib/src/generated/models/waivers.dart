// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'waivers.g.dart';

@JsonSerializable()
class Waivers {
  const Waivers({
    required this.charge,
    required this.reason,
    required this.waivedBy,
    required this.approvedBy,
    required this.waivedAt,
  });

  factory Waivers.fromJson(Map<String, Object?> json) =>
      _$WaiversFromJson(json);

  final String charge;
  final String reason;
  @JsonKey(name: 'waived_by')
  final int? waivedBy;
  @JsonKey(name: 'approved_by')
  final int? approvedBy;
  @JsonKey(name: 'waived_at')
  final String? waivedAt;

  Map<String, Object?> toJson() => _$WaiversToJson(this);
}
