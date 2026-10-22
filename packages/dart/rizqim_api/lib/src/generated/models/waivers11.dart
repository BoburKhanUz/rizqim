// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'waivers11.g.dart';

@JsonSerializable()
class Waivers11 {
  const Waivers11({
    required this.charge,
    required this.reason,
    required this.waivedBy,
    required this.approvedBy,
    required this.waivedAt,
  });

  factory Waivers11.fromJson(Map<String, Object?> json) =>
      _$Waivers11FromJson(json);

  final String charge;
  final String reason;
  @JsonKey(name: 'waived_by')
  final int? waivedBy;
  @JsonKey(name: 'approved_by')
  final int? approvedBy;
  @JsonKey(name: 'waived_at')
  final String? waivedAt;

  Map<String, Object?> toJson() => _$Waivers11ToJson(this);
}
