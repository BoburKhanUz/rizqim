// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'period2.g.dart';

@JsonSerializable()
class Period2 {
  const Period2({
    required this.from,
    required this.to,
    required this.days,
    required this.timezone,
    required this.basis,
    required this.groupBy,
  });

  factory Period2.fromJson(Map<String, Object?> json) =>
      _$Period2FromJson(json);

  final String from;
  final String to;
  final int days;
  final String timezone;
  final String basis;
  @JsonKey(name: 'group_by')
  final String groupBy;

  Map<String, Object?> toJson() => _$Period2ToJson(this);
}
