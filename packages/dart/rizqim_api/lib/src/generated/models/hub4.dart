// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'hub4.g.dart';

@JsonSerializable()
class Hub4 {
  const Hub4({required this.device, required this.name, required this.port});

  factory Hub4.fromJson(Map<String, Object?> json) => _$Hub4FromJson(json);

  final String? device;
  final String? name;
  final int port;

  Map<String, Object?> toJson() => _$Hub4ToJson(this);
}
