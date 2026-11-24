// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'branches.dart';

part 'data7.g.dart';

@JsonSerializable()
class Data7 {
  const Data7({
    required this.storeId,
    required this.branches,
    required this.generatedAt,
  });

  factory Data7.fromJson(Map<String, Object?> json) => _$Data7FromJson(json);

  @JsonKey(name: 'store_id')
  final int storeId;
  final List<Branches> branches;
  @JsonKey(name: 'generated_at')
  final String? generatedAt;

  Map<String, Object?> toJson() => _$Data7ToJson(this);
}
