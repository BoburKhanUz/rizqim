// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data315.g.dart';

@JsonSerializable()
class Data315 {
  const Data315({required this.received});

  factory Data315.fromJson(Map<String, Object?> json) =>
      _$Data315FromJson(json);

  final bool received;

  Map<String, Object?> toJson() => _$Data315ToJson(this);
}
