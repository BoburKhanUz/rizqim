// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device23.dart';
import 'errors2.dart';

part 'data202.g.dart';

@JsonSerializable()
class Data202 {
  const Data202({
    required this.serverTime,
    required this.device,
    required this.errors,
    required this.errorsCount,
  });

  factory Data202.fromJson(Map<String, Object?> json) =>
      _$Data202FromJson(json);

  @JsonKey(name: 'server_time')
  final String serverTime;
  final Device23 device;
  final List<Errors2> errors;
  @JsonKey(name: 'errors_count')
  final int errorsCount;

  Map<String, Object?> toJson() => _$Data202ToJson(this);
}
