// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'releases.dart';

part 'data342.g.dart';

@JsonSerializable()
class Data342 {
  const Data342({required this.releases});

  factory Data342.fromJson(Map<String, Object?> json) =>
      _$Data342FromJson(json);

  final List<Releases> releases;

  Map<String, Object?> toJson() => _$Data342ToJson(this);
}
