// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data248.g.dart';

@JsonSerializable()
class Data248 {
  const Data248({
    required this.id,
    required this.name,
    required this.serviceState,
    required this.serviceStateAt,
  });

  factory Data248.fromJson(Map<String, Object?> json) =>
      _$Data248FromJson(json);

  final int id;
  final String name;
  @JsonKey(name: 'service_state')
  final String? serviceState;
  @JsonKey(name: 'service_state_at')
  final String? serviceStateAt;

  Map<String, Object?> toJson() => _$Data248ToJson(this);
}
