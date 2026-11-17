// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'reverb.g.dart';

@JsonSerializable()
class Reverb {
  const Reverb({
    required this.key,
    required this.host,
    required this.port,
    required this.scheme,
  });

  factory Reverb.fromJson(Map<String, Object?> json) => _$ReverbFromJson(json);

  final String key;
  final String? host;
  final int? port;
  final String? scheme;

  Map<String, Object?> toJson() => _$ReverbToJson(this);
}
