// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'releases.dart';

part 'data343.g.dart';

@JsonSerializable()
class Data343 {
  const Data343({required this.releases});

  factory Data343.fromJson(Map<String, Object?> json) =>
      _$Data343FromJson(json);

  final List<Releases> releases;

  Map<String, Object?> toJson() => _$Data343ToJson(this);
}
