// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data351.g.dart';

@JsonSerializable()
class Data351 {
  const Data351({
    required this.id,
    required this.name,
    required this.serviceState,
    required this.serviceStateAt,
  });

  factory Data351.fromJson(Map<String, Object?> json) =>
      _$Data351FromJson(json);

  final int id;
  final String name;
  @JsonKey(name: 'service_state')
  final String? serviceState;
  @JsonKey(name: 'service_state_at')
  final String? serviceStateAt;

  Map<String, Object?> toJson() => _$Data351ToJson(this);
}
