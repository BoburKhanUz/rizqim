// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'blocked.g.dart';

@JsonSerializable()
class Blocked {
  const Blocked({required this.at, required this.reason});

  factory Blocked.fromJson(Map<String, Object?> json) =>
      _$BlockedFromJson(json);

  final String? at;
  final String? reason;

  Map<String, Object?> toJson() => _$BlockedToJson(this);
}
