// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'releases.dart';

part 'data339.g.dart';

@JsonSerializable()
class Data339 {
  const Data339({required this.releases});

  factory Data339.fromJson(Map<String, Object?> json) =>
      _$Data339FromJson(json);

  final List<Releases> releases;

  Map<String, Object?> toJson() => _$Data339ToJson(this);
}
