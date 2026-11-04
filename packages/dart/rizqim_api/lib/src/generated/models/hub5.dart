// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'hub5.g.dart';

@JsonSerializable()
class Hub5 {
  const Hub5({required this.device, required this.name, required this.port});

  factory Hub5.fromJson(Map<String, Object?> json) => _$Hub5FromJson(json);

  final String? device;
  final String? name;
  final int port;

  Map<String, Object?> toJson() => _$Hub5ToJson(this);
}
