// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'hub.g.dart';

@JsonSerializable()
class Hub {
  const Hub({required this.device, required this.name, required this.chosen});

  factory Hub.fromJson(Map<String, Object?> json) => _$HubFromJson(json);

  final String device;
  final String? name;
  final bool chosen;

  Map<String, Object?> toJson() => _$HubToJson(this);
}
