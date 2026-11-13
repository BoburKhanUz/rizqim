// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'blocked6.g.dart';

@JsonSerializable()
class Blocked6 {
  const Blocked6({required this.at, required this.reason});

  factory Blocked6.fromJson(Map<String, Object?> json) =>
      _$Blocked6FromJson(json);

  final String? at;
  final String? reason;

  Map<String, Object?> toJson() => _$Blocked6ToJson(this);
}
