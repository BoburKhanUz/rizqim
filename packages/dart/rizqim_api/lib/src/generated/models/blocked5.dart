// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'blocked5.g.dart';

@JsonSerializable()
class Blocked5 {
  const Blocked5({required this.at, required this.reason});

  factory Blocked5.fromJson(Map<String, Object?> json) =>
      _$Blocked5FromJson(json);

  final String? at;
  final String? reason;

  Map<String, Object?> toJson() => _$Blocked5ToJson(this);
}
