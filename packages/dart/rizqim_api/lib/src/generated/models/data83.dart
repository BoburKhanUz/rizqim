// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data82.dart';

part 'data83.g.dart';

@JsonSerializable()
class Data83 {
  const Data83({
    required this.data,
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
    required this.from,
    required this.to,
  });

  factory Data83.fromJson(Map<String, Object?> json) => _$Data83FromJson(json);

  final List<Data82> data;
  @JsonKey(name: 'current_page')
  final int currentPage;
  @JsonKey(name: 'last_page')
  final int lastPage;
  @JsonKey(name: 'per_page')
  final int perPage;
  final int total;
  final int? from;
  final int? to;

  Map<String, Object?> toJson() => _$Data83ToJson(this);
}
