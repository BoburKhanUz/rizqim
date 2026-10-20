// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'type.dart';

part 'device2.g.dart';

@JsonSerializable()
class Device2 {
  const Device2({
    this.type,
    this.uuid,
    this.name,
    this.platform,
    this.appVersion,
    this.pushToken,
  });

  factory Device2.fromJson(Map<String, Object?> json) =>
      _$Device2FromJson(json);

  final Type? type;
  final String? uuid;
  final String? name;
  final String? platform;
  @JsonKey(name: 'app_version')
  final String? appVersion;
  @JsonKey(name: 'push_token')
  final String? pushToken;

  Map<String, Object?> toJson() => _$Device2ToJson(this);
}
