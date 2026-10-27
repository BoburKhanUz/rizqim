// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'type.dart';

part 'device3.g.dart';

@JsonSerializable()
class Device3 {
  const Device3({
    required this.type,
    this.uuid,
    this.name,
    this.platform,
    this.appVersion,
    this.pushToken,
  });

  factory Device3.fromJson(Map<String, Object?> json) =>
      _$Device3FromJson(json);

  final Type type;
  final String? uuid;
  final String? name;
  final String? platform;
  @JsonKey(name: 'app_version')
  final String? appVersion;
  @JsonKey(name: 'push_token')
  final String? pushToken;

  Map<String, Object?> toJson() => _$Device3ToJson(this);
}
