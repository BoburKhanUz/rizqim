// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data200.g.dart';

@JsonSerializable()
class Data200 {
  const Data200({required this.stored});

  factory Data200.fromJson(Map<String, Object?> json) =>
      _$Data200FromJson(json);

  final bool stored;

  Map<String, Object?> toJson() => _$Data200ToJson(this);
}
