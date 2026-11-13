// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'blocked4.g.dart';

@JsonSerializable()
class Blocked4 {
  const Blocked4({required this.at, required this.reason});

  factory Blocked4.fromJson(Map<String, Object?> json) =>
      _$Blocked4FromJson(json);

  final String? at;
  final String? reason;

  Map<String, Object?> toJson() => _$Blocked4ToJson(this);
}
