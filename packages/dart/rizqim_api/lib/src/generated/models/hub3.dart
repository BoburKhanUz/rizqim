// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'hub3.g.dart';

@JsonSerializable()
class Hub3 {
  const Hub3({required this.device, required this.name, required this.chosen});

  factory Hub3.fromJson(Map<String, Object?> json) => _$Hub3FromJson(json);

  final String device;
  final String? name;
  final bool chosen;

  Map<String, Object?> toJson() => _$Hub3ToJson(this);
}
