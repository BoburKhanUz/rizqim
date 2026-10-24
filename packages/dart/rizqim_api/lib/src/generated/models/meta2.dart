// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'meta2.g.dart';

@JsonSerializable()
class Meta2 {
  const Meta2({
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
  });

  factory Meta2.fromJson(Map<String, Object?> json) => _$Meta2FromJson(json);

  @JsonKey(name: 'current_page')
  final int currentPage;
  @JsonKey(name: 'last_page')
  final int lastPage;
  @JsonKey(name: 'per_page')
  final int perPage;
  final int total;

  Map<String, Object?> toJson() => _$Meta2ToJson(this);
}
