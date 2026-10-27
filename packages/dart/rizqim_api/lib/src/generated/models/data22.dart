// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'items.dart';
import 'pagination.dart';

part 'data22.g.dart';

@JsonSerializable()
class Data22 {
  const Data22({
    required this.items,
    required this.unreadCount,
    required this.pagination,
  });

  factory Data22.fromJson(Map<String, Object?> json) => _$Data22FromJson(json);

  final List<Items> items;
  @JsonKey(name: 'unread_count')
  final int unreadCount;
  final Pagination pagination;

  Map<String, Object?> toJson() => _$Data22ToJson(this);
}
