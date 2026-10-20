// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data37.dart';

part 'data38.g.dart';

@JsonSerializable()
class Data38 {
  const Data38({
    required this.currentPage,
    required this.data,
    required this.perPage,
    required this.total,
    required this.lastPage,
  });

  factory Data38.fromJson(Map<String, Object?> json) => _$Data38FromJson(json);

  @JsonKey(name: 'current_page')
  final int currentPage;
  final List<Data37> data;
  @JsonKey(name: 'per_page')
  final int perPage;
  final int total;
  @JsonKey(name: 'last_page')
  final int lastPage;

  Map<String, Object?> toJson() => _$Data38ToJson(this);
}
