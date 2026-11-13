// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'hub2.g.dart';

@JsonSerializable()
class Hub2 {
  const Hub2({required this.device, required this.name, required this.chosen});

  factory Hub2.fromJson(Map<String, Object?> json) => _$Hub2FromJson(json);

  final String device;
  final String? name;
  final bool chosen;

  Map<String, Object?> toJson() => _$Hub2ToJson(this);
}
