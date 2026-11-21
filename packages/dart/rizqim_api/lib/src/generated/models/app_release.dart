// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'app_release.g.dart';

@JsonSerializable()
class AppRelease {
  const AppRelease({
    required this.current,
    required this.latest,
    required this.minimum,
    required this.state,
    required this.url,
    required this.notes,
    required this.minimumFrom,
  });

  factory AppRelease.fromJson(Map<String, Object?> json) =>
      _$AppReleaseFromJson(json);

  final String? current;
  final String latest;
  final String? minimum;
  final String state;
  final String url;
  final String? notes;
  @JsonKey(name: 'minimum_from')
  final String? minimumFrom;

  Map<String, Object?> toJson() => _$AppReleaseToJson(this);
}
