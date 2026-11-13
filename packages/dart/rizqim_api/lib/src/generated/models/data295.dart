// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device23.dart';
import 'errors2.dart';

part 'data295.g.dart';

@JsonSerializable()
class Data295 {
  const Data295({
    required this.serverTime,
    required this.device,
    required this.errors,
    required this.errorsCount,
  });

  factory Data295.fromJson(Map<String, Object?> json) =>
      _$Data295FromJson(json);

  @JsonKey(name: 'server_time')
  final String serverTime;
  final Device23 device;
  final List<Errors2> errors;
  @JsonKey(name: 'errors_count')
  final int errorsCount;

  Map<String, Object?> toJson() => _$Data295ToJson(this);
}
