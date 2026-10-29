// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device22.dart';
import 'errors2.dart';

part 'data207.g.dart';

@JsonSerializable()
class Data207 {
  const Data207({
    required this.serverTime,
    required this.device,
    required this.errors,
    required this.errorsCount,
  });

  factory Data207.fromJson(Map<String, Object?> json) =>
      _$Data207FromJson(json);

  @JsonKey(name: 'server_time')
  final String serverTime;
  final Device22 device;
  final List<Errors2> errors;
  @JsonKey(name: 'errors_count')
  final int errorsCount;

  Map<String, Object?> toJson() => _$Data207ToJson(this);
}
