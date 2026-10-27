// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'channel6.dart';
import 'branch12.dart';
import 'category.dart';
import 'products.dart';
import 'availability.dart';

part 'data160.g.dart';

@JsonSerializable()
class Data160 {
  const Data160({
    required this.channel,
    required this.menuVersion,
    required this.branch,
    required this.categories,
    required this.products,
    required this.availability,
  });

  factory Data160.fromJson(Map<String, Object?> json) =>
      _$Data160FromJson(json);

  final Channel6 channel;
  @JsonKey(name: 'menu_version')
  final String menuVersion;
  final Branch12 branch;
  final List<Category> categories;
  final List<Products> products;
  final Availability availability;

  Map<String, Object?> toJson() => _$Data160ToJson(this);
}
